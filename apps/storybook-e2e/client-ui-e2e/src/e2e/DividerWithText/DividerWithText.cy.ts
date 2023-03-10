describe('client-ui: DividerWithText component', () => {
  beforeEach(() => cy.visit('/iframe.html?id=dividerwithtext--primary'));

  it('should render the component', () => {
    cy.get('h1').should('contain', 'Welcome to DividerWithText!');
  });
});
