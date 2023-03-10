describe('client-ui: Loader component', () => {
  beforeEach(() => cy.visit('/iframe.html?id=loader--primary'));

  it('should render the component', () => {
    cy.get('h1').should('contain', 'Welcome to Loader!');
  });
});
