describe('client-ui: Menu component', () => {
  beforeEach(() => cy.visit('/iframe.html?id=menu--primary'));

  it('should render the component', () => {
    cy.get('h1').should('contain', 'Welcome to Menu!');
  });
});
